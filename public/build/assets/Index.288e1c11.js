import{_ as K}from"./Authenticated.627074d1.js";import{_ as j}from"./Button.c68f0fc3.js";import{O as z,P as G}from"./Paginator.84e2395e.js";import{S as U}from"./SearchInput.dbef3e56.js";import{o as d,c as w,r as y,a as u,e as m,p as C,K as E,F as O,l as B,t as a,M as _,m as f,T as M,w as S,f as x,E as L,g,G as R,d as s,n as b}from"./app.9498d3c0.js";import{_ as F}from"./_plugin-vue_export-helper.cdc0426e.js";function T(e){return e===0?!1:Array.isArray(e)&&e.length===0?!0:!e}function q(e){return(...t)=>!e(...t)}function Q(e,t){return e===void 0&&(e="undefined"),e===null&&(e="null"),e===!1&&(e="false"),e.toString().toLowerCase().indexOf(t.trim())!==-1}function H(e,t,l,h){return t?e.filter(r=>Q(h(r,l),t)).sort((r,n)=>h(r,l).length-h(n,l).length):e}function J(e){return e.filter(t=>!t.$isLabel)}function P(e,t){return l=>l.reduce((h,r)=>r[e]&&r[e].length?(h.push({$groupLabel:r[t],$isLabel:!0}),h.concat(r[e])):h,[])}function W(e,t,l,h,r){return n=>n.map(i=>{if(!i[l])return console.warn("Options passed to vue-multiselect do not contain groups, despite the config."),[];const p=H(i[l],e,t,r);return p.length?{[h]:i[h],[l]:p}:[]})}const D=(...e)=>t=>e.reduce((l,h)=>h(l),t);var X={data(){return{search:"",isOpen:!1,preferredOpenDirection:"below",optimizedHeight:this.maxHeight}},props:{internalSearch:{type:Boolean,default:!0},options:{type:Array,required:!0},multiple:{type:Boolean,default:!1},trackBy:{type:String},label:{type:String},searchable:{type:Boolean,default:!0},clearOnSelect:{type:Boolean,default:!0},hideSelected:{type:Boolean,default:!1},placeholder:{type:String,default:"Select option"},allowEmpty:{type:Boolean,default:!0},resetAfter:{type:Boolean,default:!1},closeOnSelect:{type:Boolean,default:!0},customLabel:{type:Function,default(e,t){return T(e)?"":t?e[t]:e}},taggable:{type:Boolean,default:!1},tagPlaceholder:{type:String,default:"Press enter to create a tag"},tagPosition:{type:String,default:"top"},max:{type:[Number,Boolean],default:!1},id:{default:null},optionsLimit:{type:Number,default:1e3},groupValues:{type:String},groupLabel:{type:String},groupSelect:{type:Boolean,default:!1},blockKeys:{type:Array,default(){return[]}},preserveSearch:{type:Boolean,default:!1},preselectFirst:{type:Boolean,default:!1}},mounted(){!this.multiple&&this.max&&console.warn("[Vue-Multiselect warn]: Max prop should not be used when prop Multiple equals false."),this.preselectFirst&&!this.internalValue.length&&this.options.length&&this.select(this.filteredOptions[0])},computed:{internalValue(){return this.modelValue||this.modelValue===0?Array.isArray(this.modelValue)?this.modelValue:[this.modelValue]:[]},filteredOptions(){const e=this.search||"",t=e.toLowerCase().trim();let l=this.options.concat();return this.internalSearch?l=this.groupValues?this.filterAndFlat(l,t,this.label):H(l,t,this.label,this.customLabel):l=this.groupValues?P(this.groupValues,this.groupLabel)(l):l,l=this.hideSelected?l.filter(q(this.isSelected)):l,this.taggable&&t.length&&!this.isExistingOption(t)&&(this.tagPosition==="bottom"?l.push({isTag:!0,label:e}):l.unshift({isTag:!0,label:e})),l.slice(0,this.optionsLimit)},valueKeys(){return this.trackBy?this.internalValue.map(e=>e[this.trackBy]):this.internalValue},optionKeys(){return(this.groupValues?this.flatAndStrip(this.options):this.options).map(t=>this.customLabel(t,this.label).toString().toLowerCase())},currentOptionLabel(){return this.multiple?this.searchable?"":this.placeholder:this.internalValue.length?this.getOptionLabel(this.internalValue[0]):this.searchable?"":this.placeholder}},watch:{internalValue(){this.resetAfter&&this.internalValue.length&&(this.search="",this.$emit("update:modelValue",this.multiple?[]:null))},search(){this.$emit("search-change",this.search)}},emits:["open","search-change","close","select","update:modelValue","remove","tag"],methods:{getValue(){return this.multiple?this.internalValue:this.internalValue.length===0?null:this.internalValue[0]},filterAndFlat(e,t,l){return D(W(t,l,this.groupValues,this.groupLabel,this.customLabel),P(this.groupValues,this.groupLabel))(e)},flatAndStrip(e){return D(P(this.groupValues,this.groupLabel),J)(e)},updateSearch(e){this.search=e},isExistingOption(e){return this.options?this.optionKeys.indexOf(e)>-1:!1},isSelected(e){const t=this.trackBy?e[this.trackBy]:e;return this.valueKeys.indexOf(t)>-1},isOptionDisabled(e){return!!e.$isDisabled},getOptionLabel(e){if(T(e))return"";if(e.isTag)return e.label;if(e.$isLabel)return e.$groupLabel;const t=this.customLabel(e,this.label);return T(t)?"":t},select(e,t){if(e.$isLabel&&this.groupSelect){this.selectGroup(e);return}if(!(this.blockKeys.indexOf(t)!==-1||this.disabled||e.$isDisabled||e.$isLabel)&&!(this.max&&this.multiple&&this.internalValue.length===this.max)&&!(t==="Tab"&&!this.pointerDirty)){if(e.isTag)this.$emit("tag",e.label,this.id),this.search="",this.closeOnSelect&&!this.multiple&&this.deactivate();else{if(this.isSelected(e)){t!=="Tab"&&this.removeElement(e);return}this.$emit("select",e,this.id),this.multiple?this.$emit("update:modelValue",this.internalValue.concat([e])):this.$emit("update:modelValue",e),this.clearOnSelect&&(this.search="")}this.closeOnSelect&&this.deactivate()}},selectGroup(e){const t=this.options.find(l=>l[this.groupLabel]===e.$groupLabel);if(!!t){if(this.wholeGroupSelected(t)){this.$emit("remove",t[this.groupValues],this.id);const l=this.internalValue.filter(h=>t[this.groupValues].indexOf(h)===-1);this.$emit("update:modelValue",l)}else{const l=t[this.groupValues].filter(h=>!(this.isOptionDisabled(h)||this.isSelected(h)));this.$emit("select",l,this.id),this.$emit("update:modelValue",this.internalValue.concat(l))}this.closeOnSelect&&this.deactivate()}},wholeGroupSelected(e){return e[this.groupValues].every(t=>this.isSelected(t)||this.isOptionDisabled(t))},wholeGroupDisabled(e){return e[this.groupValues].every(this.isOptionDisabled)},removeElement(e,t=!0){if(this.disabled||e.$isDisabled)return;if(!this.allowEmpty&&this.internalValue.length<=1){this.deactivate();return}const l=typeof e=="object"?this.valueKeys.indexOf(e[this.trackBy]):this.valueKeys.indexOf(e);if(this.$emit("remove",e,this.id),this.multiple){const h=this.internalValue.slice(0,l).concat(this.internalValue.slice(l+1));this.$emit("update:modelValue",h)}else this.$emit("update:modelValue",null);this.closeOnSelect&&t&&this.deactivate()},removeLastElement(){this.blockKeys.indexOf("Delete")===-1&&this.search.length===0&&Array.isArray(this.internalValue)&&this.internalValue.length&&this.removeElement(this.internalValue[this.internalValue.length-1],!1)},activate(){this.isOpen||this.disabled||(this.adjustPosition(),this.groupValues&&this.pointer===0&&this.filteredOptions.length&&(this.pointer=1),this.isOpen=!0,this.searchable?(this.preserveSearch||(this.search=""),this.$nextTick(()=>this.$refs.search&&this.$refs.search.focus())):this.$el.focus(),this.$emit("open",this.id))},deactivate(){!this.isOpen||(this.isOpen=!1,this.searchable?this.$refs.search&&this.$refs.search.blur():this.$el.blur(),this.preserveSearch||(this.search=""),this.$emit("close",this.getValue(),this.id))},toggle(){this.isOpen?this.deactivate():this.activate()},adjustPosition(){if(typeof window>"u")return;const e=this.$el.getBoundingClientRect().top,t=window.innerHeight-this.$el.getBoundingClientRect().bottom;t>this.maxHeight||t>e||this.openDirection==="below"||this.openDirection==="bottom"?(this.preferredOpenDirection="below",this.optimizedHeight=Math.min(t-40,this.maxHeight)):(this.preferredOpenDirection="above",this.optimizedHeight=Math.min(e-40,this.maxHeight))}}},Y={data(){return{pointer:0,pointerDirty:!1}},props:{showPointer:{type:Boolean,default:!0},optionHeight:{type:Number,default:40}},computed:{pointerPosition(){return this.pointer*this.optionHeight},visibleElements(){return this.optimizedHeight/this.optionHeight}},watch:{filteredOptions(){this.pointerAdjust()},isOpen(){this.pointerDirty=!1},pointer(){this.$refs.search&&this.$refs.search.setAttribute("aria-activedescendant",this.id+"-"+this.pointer.toString())}},methods:{optionHighlight(e,t){return{"multiselect__option--highlight":e===this.pointer&&this.showPointer,"multiselect__option--selected":this.isSelected(t)}},groupHighlight(e,t){if(!this.groupSelect)return["multiselect__option--disabled",{"multiselect__option--group":t.$isLabel}];const l=this.options.find(h=>h[this.groupLabel]===t.$groupLabel);return l&&!this.wholeGroupDisabled(l)?["multiselect__option--group",{"multiselect__option--highlight":e===this.pointer&&this.showPointer},{"multiselect__option--group-selected":this.wholeGroupSelected(l)}]:"multiselect__option--disabled"},addPointerElement({key:e}="Enter"){this.filteredOptions.length>0&&this.select(this.filteredOptions[this.pointer],e),this.pointerReset()},pointerForward(){this.pointer<this.filteredOptions.length-1&&(this.pointer++,this.$refs.list.scrollTop<=this.pointerPosition-(this.visibleElements-1)*this.optionHeight&&(this.$refs.list.scrollTop=this.pointerPosition-(this.visibleElements-1)*this.optionHeight),this.filteredOptions[this.pointer]&&this.filteredOptions[this.pointer].$isLabel&&!this.groupSelect&&this.pointerForward()),this.pointerDirty=!0},pointerBackward(){this.pointer>0?(this.pointer--,this.$refs.list.scrollTop>=this.pointerPosition&&(this.$refs.list.scrollTop=this.pointerPosition),this.filteredOptions[this.pointer]&&this.filteredOptions[this.pointer].$isLabel&&!this.groupSelect&&this.pointerBackward()):this.filteredOptions[this.pointer]&&this.filteredOptions[0].$isLabel&&!this.groupSelect&&this.pointerForward(),this.pointerDirty=!0},pointerReset(){!this.closeOnSelect||(this.pointer=0,this.$refs.list&&(this.$refs.list.scrollTop=0))},pointerAdjust(){this.pointer>=this.filteredOptions.length-1&&(this.pointer=this.filteredOptions.length?this.filteredOptions.length-1:0),this.filteredOptions.length>0&&this.filteredOptions[this.pointer].$isLabel&&!this.groupSelect&&this.pointerForward()},pointerSet(e){this.pointer=e,this.pointerDirty=!0}}},A={name:"vue-multiselect",mixins:[X,Y],props:{name:{type:String,default:""},modelValue:{type:null,default(){return[]}},selectLabel:{type:String,default:"Press enter to select"},selectGroupLabel:{type:String,default:"Press enter to select group"},selectedLabel:{type:String,default:"Selected"},deselectLabel:{type:String,default:"Press enter to remove"},deselectGroupLabel:{type:String,default:"Press enter to deselect group"},showLabels:{type:Boolean,default:!0},limit:{type:Number,default:99999},maxHeight:{type:Number,default:300},limitText:{type:Function,default:e=>`and ${e} more`},loading:{type:Boolean,default:!1},disabled:{type:Boolean,default:!1},openDirection:{type:String,default:""},showNoOptions:{type:Boolean,default:!0},showNoResults:{type:Boolean,default:!0},tabindex:{type:Number,default:0}},computed:{isSingleLabelVisible(){return(this.singleValue||this.singleValue===0)&&(!this.isOpen||!this.searchable)&&!this.visibleValues.length},isPlaceholderVisible(){return!this.internalValue.length&&(!this.searchable||!this.isOpen)},visibleValues(){return this.multiple?this.internalValue.slice(0,this.limit):[]},singleValue(){return this.internalValue[0]},deselectLabelText(){return this.showLabels?this.deselectLabel:""},deselectGroupLabelText(){return this.showLabels?this.deselectGroupLabel:""},selectLabelText(){return this.showLabels?this.selectLabel:""},selectGroupLabelText(){return this.showLabels?this.selectGroupLabel:""},selectedLabelText(){return this.showLabels?this.selectedLabel:""},inputStyle(){return this.searchable||this.multiple&&this.modelValue&&this.modelValue.length?this.isOpen?{width:"100%"}:{width:"0",position:"absolute",padding:"0"}:""},contentStyle(){return this.options.length?{display:"inline-block"}:{display:"block"}},isAbove(){return this.openDirection==="above"||this.openDirection==="top"?!0:this.openDirection==="below"||this.openDirection==="bottom"?!1:this.preferredOpenDirection==="above"},showSearchInput(){return this.searchable&&(this.hasSingleSelectedSlot&&(this.visibleSingleValue||this.visibleSingleValue===0)?this.isOpen:!0)}}};const Z={ref:"tags",class:"multiselect__tags"},I={class:"multiselect__tags-wrap"},ee={class:"multiselect__spinner"},te={key:0},se={class:"multiselect__option"},le={class:"multiselect__option"},ie=x("No elements found. Consider changing the search query."),re={class:"multiselect__option"},oe=x("List is empty.");function ne(e,t,l,h,r,n){return d(),w("div",{tabindex:e.searchable?-1:l.tabindex,class:[{"multiselect--active":e.isOpen,"multiselect--disabled":l.disabled,"multiselect--above":n.isAbove},"multiselect"],onFocus:t[14]||(t[14]=i=>e.activate()),onBlur:t[15]||(t[15]=i=>e.searchable?!1:e.deactivate()),onKeydown:[t[16]||(t[16]=_(m(i=>e.pointerForward(),["self","prevent"]),["down"])),t[17]||(t[17]=_(m(i=>e.pointerBackward(),["self","prevent"]),["up"]))],onKeypress:t[18]||(t[18]=_(m(i=>e.addPointerElement(i),["stop","self"]),["enter","tab"])),onKeyup:t[19]||(t[19]=_(i=>e.deactivate(),["esc"])),role:"combobox","aria-owns":"listbox-"+e.id},[y(e.$slots,"caret",{toggle:e.toggle},()=>[u("div",{onMousedown:t[1]||(t[1]=m(i=>e.toggle(),["prevent","stop"])),class:"multiselect__select"},null,32)]),y(e.$slots,"clear",{search:e.search}),u("div",Z,[y(e.$slots,"selection",{search:e.search,remove:e.removeElement,values:n.visibleValues,isOpen:e.isOpen},()=>[C(u("div",I,[(d(!0),w(O,null,B(n.visibleValues,(i,p)=>y(e.$slots,"tag",{option:i,search:e.search,remove:e.removeElement},()=>[(d(),w("span",{class:"multiselect__tag",key:p},[u("span",{textContent:a(e.getOptionLabel(i))},null,8,["textContent"]),u("i",{tabindex:"1",onKeypress:_(m(k=>e.removeElement(i),["prevent"]),["enter"]),onMousedown:m(k=>e.removeElement(i),["prevent"]),class:"multiselect__tag-icon"},null,40,["onKeypress","onMousedown"])]))])),256))],512),[[E,n.visibleValues.length>0]]),e.internalValue&&e.internalValue.length>l.limit?y(e.$slots,"limit",{key:0},()=>[u("strong",{class:"multiselect__strong",textContent:a(l.limitText(e.internalValue.length-l.limit))},null,8,["textContent"])]):f("v-if",!0)]),u(M,{name:"multiselect__loading"},{default:S(()=>[y(e.$slots,"loading",{},()=>[C(u("div",ee,null,512),[[E,l.loading]])])]),_:3}),e.searchable?(d(),w("input",{key:0,ref:"search",name:l.name,id:e.id,type:"text",autocomplete:"off",spellcheck:"false",placeholder:e.placeholder,style:n.inputStyle,value:e.search,disabled:l.disabled,tabindex:l.tabindex,onInput:t[2]||(t[2]=i=>e.updateSearch(i.target.value)),onFocus:t[3]||(t[3]=m(i=>e.activate(),["prevent"])),onBlur:t[4]||(t[4]=m(i=>e.deactivate(),["prevent"])),onKeyup:t[5]||(t[5]=_(i=>e.deactivate(),["esc"])),onKeydown:[t[6]||(t[6]=_(m(i=>e.pointerForward(),["prevent"]),["down"])),t[7]||(t[7]=_(m(i=>e.pointerBackward(),["prevent"]),["up"])),t[9]||(t[9]=_(m(i=>e.removeLastElement(),["stop"]),["delete"]))],onKeypress:t[8]||(t[8]=_(m(i=>e.addPointerElement(i),["prevent","stop","self"]),["enter"])),class:"multiselect__input","aria-controls":"listbox-"+e.id},null,44,["name","id","placeholder","value","disabled","tabindex","aria-controls"])):f("v-if",!0),n.isSingleLabelVisible?(d(),w("span",{key:1,class:"multiselect__single",onMousedown:t[10]||(t[10]=m((...i)=>e.toggle&&e.toggle(...i),["prevent"]))},[y(e.$slots,"singleLabel",{option:n.singleValue},()=>[x(a(e.currentOptionLabel),1)])],32)):f("v-if",!0),n.isPlaceholderVisible?(d(),w("span",{key:2,class:"multiselect__placeholder",onMousedown:t[11]||(t[11]=m((...i)=>e.toggle&&e.toggle(...i),["prevent"]))},[y(e.$slots,"placeholder",{},()=>[x(a(e.placeholder),1)])],32)):f("v-if",!0)],512),u(M,{name:"multiselect"},{default:S(()=>[C(u("div",{class:"multiselect__content-wrapper",onFocus:t[12]||(t[12]=(...i)=>e.activate&&e.activate(...i)),tabindex:"-1",onMousedown:t[13]||(t[13]=m(()=>{},["prevent"])),style:{maxHeight:e.optimizedHeight+"px"},ref:"list"},[u("ul",{class:"multiselect__content",style:n.contentStyle,role:"listbox",id:"listbox-"+e.id},[y(e.$slots,"beforeList"),e.multiple&&e.max===e.internalValue.length?(d(),w("li",te,[u("span",se,[y(e.$slots,"maxElements",{},()=>[x("Maximum of "+a(e.max)+" options selected. First remove a selected option to select another.",1)])])])):f("v-if",!0),!e.max||e.internalValue.length<e.max?(d(!0),w(O,{key:1},B(e.filteredOptions,(i,p)=>(d(),w("li",{class:"multiselect__element",key:p,id:e.id+"-"+p,role:i&&(i.$isLabel||i.$isDisabled)?null:"option"},[i&&(i.$isLabel||i.$isDisabled)?f("v-if",!0):(d(),w("span",{key:0,class:[e.optionHighlight(p,i),"multiselect__option"],onClick:m(k=>e.select(i),["stop"]),onMouseenter:m(k=>e.pointerSet(p),["self"]),"data-select":i&&i.isTag?e.tagPlaceholder:n.selectLabelText,"data-selected":n.selectedLabelText,"data-deselect":n.deselectLabelText},[y(e.$slots,"option",{option:i,search:e.search,index:p},()=>[u("span",null,a(e.getOptionLabel(i)),1)])],42,["onClick","onMouseenter","data-select","data-selected","data-deselect"])),i&&(i.$isLabel||i.$isDisabled)?(d(),w("span",{key:1,"data-select":e.groupSelect&&n.selectGroupLabelText,"data-deselect":e.groupSelect&&n.deselectGroupLabelText,class:[e.groupHighlight(p,i),"multiselect__option"],onMouseenter:m(k=>e.groupSelect&&e.pointerSet(p),["self"]),onMousedown:m(k=>e.selectGroup(i),["prevent"])},[y(e.$slots,"option",{option:i,search:e.search,index:p},()=>[u("span",null,a(e.getOptionLabel(i)),1)])],42,["data-select","data-deselect","onMouseenter","onMousedown"])):f("v-if",!0)],8,["id","role"]))),128)):f("v-if",!0),C(u("li",null,[u("span",le,[y(e.$slots,"noResult",{search:e.search},()=>[ie])])],512),[[E,l.showNoResults&&e.filteredOptions.length===0&&e.search&&!l.loading]]),C(u("li",null,[u("span",re,[y(e.$slots,"noOptions",{},()=>[oe])])],512),[[E,l.showNoOptions&&e.options.length===0&&!e.search&&!l.loading]]),y(e.$slots,"afterList")],12,["id"])],36),[[E,e.isOpen]])]),_:3})],42,["tabindex","aria-owns"])}A.render=ne;const ae={components:{VueMultiselect:A},props:{options:Array,allowEmpty:Boolean,deselectLabel:String,customLabel:Function},data(){return{selected:this.options[0]}},methods:{onSelected(){this.$emit("onSelected",this.selected)}}};function de(e,t,l,h,r,n){const i=L("VueMultiselect");return d(),g("div",null,[u(i,{modelValue:r.selected,"onUpdate:modelValue":[t[0]||(t[0]=p=>r.selected=p),t[1]||(t[1]=p=>n.onSelected())],options:l.options,"allow-empty":l.allowEmpty,"deselect-label":l.deselectLabel,"open-direction":"bottom","custom-label":l.customLabel,"reset-after":e.resetAfter},null,8,["modelValue","options","allow-empty","deselect-label","custom-label","reset-after"])])}const ue=F(ae,[["render",de]]),he={components:{BreezeAuthenticatedLayout:K,Button:j,MultiSelect:ue,OptionDropdown:z,Paginator:G,SearchInput:U},props:{vends:Object,vendChannelErrors:Object},created(){this.vendChannelErrorsOptions=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...this.vendChannelErrors.data]},data(){return{filters:this.getFiltersDefault(),vendChannelErrorsOptions:[]}},methods:{getFiltersDefault(){return{code:"",serialNum:"",name:"",tempHigherThan:"",hasError:"",sortKey:"",sortBy:!0,numberPerPage:100}},getHasErrorFiltersLabel(e){return`${e.desc}`},getNumberPerPageLabel({id:e,value:t}){return t!=="All"?`${t} results (page)`:`${t}`},getTotalQty(e){return e.vend_channels.filter(function(t){return t.capacity>0&&t.code<1e3}).reduce(function(t,l){return t+l.qty},0)},getTotalCapacity(e){return e.vend_channels.filter(function(t){return t.capacity>0&&t.code<1e3}).reduce(function(t,l){return t+l.capacity},0)},onNumberPerPageSelected(e){this.filters.numberPerPage=e.value,this.onSearchFilterUpdated()},onHasErrorSelected(e){this.filters.hasError=e,this.onSearchFilterUpdated()},onSearchFilterUpdated:R.exports.debounce(function(){this.$inertia.get("/vends",this.filters,{preserveState:!0,replace:!0})},500),onVendTempClicked(e){this.$inertia.get("/vend/"+e+"/temp")},onVendChannelErrorLogEmailClicked(){this.$inertia.get("/vends/channel-error-logs-email")},resetFilters(){this.filters=this.getFiltersDefault(),console.log(Object.values(this.vendChannelErrorsOptions)[0]),this.filters.hasError=Object.values(this.vendChannelErrorsOptions)[0],this.onSearchFilterUpdated()},sortTable(e){this.filters.sortKey=e,this.filters.sortBy=!this.filters.sortBy,this.onSearchFilterUpdated()}}},pe=s("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines ",-1),ce={class:"m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6"},me={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6"},ge={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},be=x(" Code "),fe=x(" Serial Num "),ye=x(" Name "),ve=x(" Temp Higher Than "),we=s("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Has Error? ",-1),_e={class:"flex justify-end mt-5"},xe={class:"flex flex-col space-y-2"},ke={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Se=s("span",null,"Showing",-1),Ve={class:"font-medium"},Oe=s("span",null,"to",-1),Le={class:"font-medium"},Be=s("span",null,"of",-1),Ce={class:"font-medium"},Ee=s("span",null,"results",-1),Te={class:"flex justify-end"},Pe={class:"mt-6 flex flex-col"},Me={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},De={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Fe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},He={class:"bg-gray-100"},Ae={class:"divide-x divide-gray-200"},$e=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"}," #",-1),Ne={scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"},Ke={class:"flex justify-center"},je={class:"pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800"},ze={key:0},Ge=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19 9l-7 7-7-7"})],-1),Ue=[Ge],Re={key:1},qe=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M5 15l7-7 7 7"})],-1),Qe=[qe],Je={scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"},We={class:"flex justify-center"},Xe={class:"pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800"},Ye={key:0},Ze=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19 9l-7 7-7-7"})],-1),Ie=[Ze],et={key:1},tt=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M5 15l7-7 7 7"})],-1),st=[tt],lt=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"}," Name",-1),it=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"}," Channels Status",-1),rt=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"}," Inventory Status",-1),ot={scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"},nt={class:"flex justify-center"},at={class:"pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800"},dt={key:0},ut=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19 9l-7 7-7-7"})],-1),ht=[ut],pt={key:1},ct=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M5 15l7-7 7 7"})],-1),mt=[ct],gt={scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"},bt={class:"flex justify-center"},ft={class:"pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800"},yt={key:0},vt=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19 9l-7 7-7-7"})],-1),wt=[vt],_t={key:1},xt=s("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[s("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M5 15l7-7 7 7"})],-1),kt=[xt],St=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"}," Serial Num",-1),Vt=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8"}," Firmware Ver",-1),Ot=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter"}," Door Opening?",-1),Lt=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter"}," Sensor Normal?",-1),Bt=s("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pr-4 pl-3 backdrop-blur backdrop-filter sm:pr-6 lg:pr-8"},[s("span",{class:"sr-only"},"Edit")],-1),Ct={class:"bg-white"},Et={class:"flex flex-col"},Tt=["onClick"],Pt={class:"flex flex-col space-y-1"},Mt={class:"grid grid-cols-[100px_minmax(100px,_1fr)_100px] gap-1"},Dt={class:"font-semibold"},Ft={class:"text-blue-600 text-sm pl-1"},Ht={class:"pl-1"},At={class:"col-span-3 inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full space-x-2"},$t=s("span",null," Total ",-1),Nt={class:"text-blue-600 text-sm"},Kt={href:"#",class:"text-indigo-600 hover:text-indigo-900"},jt=x("Edit"),zt={class:"sr-only"};function Gt(e,t,l,h,r,n){const i=L("Head"),p=L("SearchInput"),k=L("MultiSelect"),$=L("Paginator"),N=L("BreezeAuthenticatedLayout");return d(),g(O,null,[u(i,{title:"Vending Machine"}),u(N,null,{header:S(()=>[pe]),default:S(()=>[s("div",ce,[s("div",me,[s("div",ge,[u(p,{placeholderStr:"Code",modelValue:r.filters.code,"onUpdate:modelValue":t[0]||(t[0]=o=>r.filters.code=o),onInput:t[1]||(t[1]=o=>n.onSearchFilterUpdated())},{default:S(()=>[be]),_:1},8,["modelValue"]),u(p,{placeholderStr:"Serial Num",modelValue:r.filters.serialNum,"onUpdate:modelValue":t[2]||(t[2]=o=>r.filters.serialNum=o),onInput:t[3]||(t[3]=o=>n.onSearchFilterUpdated())},{default:S(()=>[fe]),_:1},8,["modelValue"]),u(p,{placeholderStr:"Name",modelValue:r.filters.name,"onUpdate:modelValue":t[4]||(t[4]=o=>r.filters.name=o),onInput:t[5]||(t[5]=o=>n.onSearchFilterUpdated())},{default:S(()=>[ye]),_:1},8,["modelValue"]),u(p,{placeholderStr:"Number",modelValue:r.filters.tempHigherThan,"onUpdate:modelValue":t[6]||(t[6]=o=>r.filters.tempHigherThan=o),onInput:t[7]||(t[7]=o=>n.onSearchFilterUpdated())},{default:S(()=>[ve]),_:1},8,["modelValue"]),s("div",null,[we,u(k,{modelValue:r.filters.hasError,"onUpdate:modelValue":t[8]||(t[8]=o=>r.filters.hasError=o),"custom-label":n.getHasErrorFiltersLabel,options:r.vendChannelErrorsOptions,"close-on-select":!0,"clear-on-select":!1,placeholder:"Select","track-by":"id","open-direction":"bottom",onOnSelected:n.onHasErrorSelected,class:"mt-1"},null,8,["modelValue","custom-label","options","onOnSelected"])])]),s("div",_e,[s("div",xe,[s("p",ke,[Se,s("span",Ve,a(l.vends.meta.from),1),Oe,s("span",Le,a(l.vends.meta.to),1),Be,s("span",Ce,a(l.vends.meta.total),1),Ee]),s("div",Te,[u(k,{modelValue:r.filters.numberPerPage,"onUpdate:modelValue":t[9]||(t[9]=o=>r.filters.numberPerPage=o),options:[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],"custom-label":n.getNumberPerPageLabel,"close-on-select":!0,"clear-on-select":!1,placeholder:"Select","track-by":"id","open-direction":"bottom",onOnSelected:n.onNumberPerPageSelected},null,8,["modelValue","custom-label","onOnSelected"])])])])]),s("div",Pe,[s("div",Me,[s("div",De,[s("table",Fe,[s("thead",He,[s("tr",Ae,[$e,s("th",Ne,[s("div",Ke,[s("a",{href:"#",class:"text-blue-600 hover:text-blue-800",onClick:t[10]||(t[10]=o=>n.sortTable("code"))}," Code "),s("div",je,[r.filters.sortKey==="code"&&r.filters.sortBy?(d(),g("span",ze,Ue)):f("",!0),r.filters.sortKey==="code"&&!r.filters.sortBy?(d(),g("span",Re,Qe)):f("",!0)])])]),s("th",Je,[s("div",We,[s("a",{href:"#",class:"text-blue-600 hover:text-blue-800",onClick:t[11]||(t[11]=o=>n.sortTable("temp"))}," Temp(C) "),s("div",Xe,[r.filters.sortKey==="temp"&&r.filters.sortBy?(d(),g("span",Ye,Ie)):f("",!0),r.filters.sortKey==="temp"&&!r.filters.sortBy?(d(),g("span",et,st)):f("",!0)])])]),lt,it,rt,s("th",ot,[s("div",nt,[s("a",{href:"#",class:"text-blue-600 hover:text-blue-800",onClick:t[12]||(t[12]=o=>n.sortTable("temp_updated_at"))}," Last Temp "),s("div",at,[r.filters.sortKey==="temp_updated_at"&&r.filters.sortBy?(d(),g("span",dt,ht)):f("",!0),r.filters.sortKey==="temp_updated_at"&&!r.filters.sortBy?(d(),g("span",pt,mt)):f("",!0)])])]),s("th",gt,[s("div",bt,[s("a",{href:"#",class:"text-blue-600 hover:text-blue-800",onClick:t[13]||(t[13]=o=>n.sortTable("coin_amount"))}," Coin Amount "),s("div",ft,[r.filters.sortKey==="coin_amount"&&r.filters.sortBy?(d(),g("span",yt,wt)):f("",!0),r.filters.sortKey==="coin_amount"&&!r.filters.sortBy?(d(),g("span",_t,kt)):f("",!0)])])]),St,Vt,Ot,Lt,Bt])]),s("tbody",Ct,[(d(!0),g(O,null,B(l.vends.data,(o,v)=>(d(),g("tr",{key:o.id,class:"divide-x divide-gray-200"},[s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8"],"text-right"])},a(l.vends.meta.from+v),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8"],"text-right"])},a(o.code),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 lg:pl-8"],"text-right"])},[s("div",Et,[s("button",{type:"button",class:b(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[o.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:c=>n.onVendTempClicked(o.id)},a(o.is_temp_error?"Error":o.temp),11,Tt)])],2),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8"],"text-center"])},a(o.name),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-normal py-2 pl-2 pr-1 text-sm font-medium text-gray-800 sm:pl-1 lg:pl-2"],"text-center"])},[(d(!0),g(O,null,B(o.vend_channels.map(function(c){return c}).filter(function(c){var V;return(V=c.vend_channel_error_logs.length)!=null?V:c.vend_channel_error_logs}),c=>(d(),g("span",Pt,[(d(!0),g(O,null,B(c.vend_channel_error_logs.filter(function(V){return!V.is_error_cleared}),V=>(d(),g("span",{class:b(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[V.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])}," #"+a(c.code)+", "+a(V.vend_channel_error.desc),3))),256))]))),256))],2),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","py-1 pl-1 pr-1 text-sm font-medium text-gray-800"],"text-center"])},[s("div",Mt,[(d(!0),g(O,null,B(o.vend_channels.map(function(c){return c}).filter(function(c){return c.capacity>0&&c.code<1e3}),c=>(d(),g("div",{class:b(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full",[c.capacity>0?"bg-gray-50 text-gray-900":"bg-red-100 text-red-800"]])},[s("div",Dt," #"+a(c.code)+", ",1),s("div",Ft,a(c.capacity-c.qty)+", ",1),s("div",Ht,a(c.qty)+"/"+a(c.capacity),1)],2))),256)),s("div",At,[$t,s("span",Nt,a(n.getTotalCapacity(o)-n.getTotalQty(o))+", ",1),s("span",null,a(n.getTotalQty(o))+"/"+a(n.getTotalCapacity(o)),1)])])],2),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8"],"text-center"])},a(o.temp_updated_at),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8"],"text-right"])},a(o.coin_amount),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8"],"text-center"])},a(o.serial_num),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8"],"text-center"])},a(o.firmware_ver),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8"],"text-center"])},a(o.is_door_open),3),s("td",{class:b([[v!==l.vends.length-1?"border-b border-gray-200":"","whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8"],"text-center"])},a(o.is_sensor_normal),3),s("td",{class:b([v!==l.vends.length-1?"border-b border-gray-200":"","relative whitespace-nowrap py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6 lg:pr-8"])},[s("a",Kt,[jt,s("span",zt,", "+a(o.name),1)])],2)]))),128))])]),u($,{links:l.vends.links,meta:l.vends.meta},null,8,["links","meta"])])])])])]),_:1})],64)}const Xt=F(he,[["render",Gt]]);export{Xt as default};
