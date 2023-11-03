import{g as u,T as M,K as E,h as re,f as r,a as d,u as c,w as m,F as O,o as a,Z as le,b as e,d as x,t as p,l as i,e as f,i as ne,c as g,n as v,k as $,O as h}from"./app.7220a7a9.js";import{_ as ie}from"./Authenticated.b1767d84.js";import{_ as y}from"./Button.26799c9c.js";import{r as D,_ as de}from"./ChannelOverview.07cf577d.js";import"./main.ebc23336.js";import{_ as w}from"./FormInput.ed978cfa.js";import{_ as B}from"./MultiSelect.3527aebb.js";import{r as ce}from"./ArrowUturnLeftIcon.2d0952d7.js";import{r as pe}from"./CheckCircleIcon.9cb78225.js";import{r as L}from"./PlusCircleIcon.6eb082f8.js";import{r as U}from"./PauseCircleIcon.2822fd2b.js";import{r as ue}from"./BackspaceIcon.e9bfc536.js";import"./open-closed.1060e560.js";import"./use-resolve-button-type.e5161c00.js";import"./RectangleStackIcon.e343ca30.js";import"./Modal.79bc2506.js";import"./disposables.4040ad08.js";import"./PencilSquareIcon.50bd1198.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.55c94d0b.js";const me={class:"font-semibold text-xl text-gray-800 leading-tight"},_e={key:0},ve={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},xe={class:"mt-6 flex flex-col"},ge={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 p-5"},fe=["onSubmit"],he={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},be={class:"sm:col-span-6"},we={class:"sm:col-span-6"},ke=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),Ce={class:"mt-1"},Se=["value"],Oe={class:"sm:col-span-6"},Ve=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),Pe={class:"mt-1"},je=["value"],Ae={class:"sm:col-span-6"},Me=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform Category ",-1),$e={class:"mt-1"},De=["value"],Be={class:"sm:col-span-6"},Ue=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Refer to Product Mapping ",-1),Ne={class:"mt-1"},Re=["value"],qe={class:"sm:col-span-3"},Fe={class:"sm:col-span-3"},Ke=e("div",{class:"sm:col-span-6"},[e("label",{for:"reserved",class:"italic text-blue-800"},' By setting "Reserved Percentage" and "Reserved Quantity", the sellable qty equivalent to whichever higher. If lower than reserved, channel becomes inactive, both default value are 0. ')],-1),Te={class:"sm:col-span-6"},Ee={class:"flex space-x-1 mt-5 justify-end"},Le=e("span",null," Back ",-1),Qe=e("span",null," Save ",-1),ze=e("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"},"Delivery Platform Product(s) ")])])],-1),Ze={key:0},Ge={key:1},He=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Product ",-1),Je={key:0,class:"text-sm text-red-600"},We={key:2},Xe={key:3},Ye=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform SubCategory ",-1),Ie={key:4,class:"sm:col-span-1"},et=e("span",null," Add ",-1),tt={key:5,class:"sm:col-span-6 flex flex-col mt-3"},st={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},ot={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},at={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},rt={class:"table-fixed min-w-full divide-y divide-gray-300"},lt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Thumbnail "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Product "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Price "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Platform SubCategory "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),nt={class:"bg-white"},it={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},dt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ct={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},pt={class:"flex justify-center"},ut=["src"],mt={class:"whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col"},_t={key:0},vt={key:1,class:"break-words"},xt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},gt={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},yt={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},ft={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ht={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},bt={class:"whitespace-nowrap py-4 text-sm text-center flex flex-col space-y-1 px-2"},wt={key:2,class:"text-xs"},kt={key:3,class:"text-xs"},Ct=e("span",{class:"text-xs"}," Unbind SKU ",-1),St={key:0},Ot=e("td",{colspan:"7",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),Vt=[Ot],Pt={key:6,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},jt=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"}," Vending Machine Binding ")])],-1),At=[jt],Mt={class:"sm:col-span-6"},$t={class:"flex space-x-1 mt-5 justify-start"},Dt={key:2},Bt={key:3},Ut={key:7,class:"sm:col-span-5"},Nt=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine ",-1),Rt={key:0,class:"text-sm text-red-600"},qt={key:8,class:"sm:col-span-1"},Ft=e("span",null," Add ",-1),Kt={key:9,class:"sm:col-span-6 flex flex-col mt-3"},Tt={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},Et={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Lt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},Qt={class:"min-w-full divide-y divide-gray-300"},zt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend Name "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," VM Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),Zt={class:"bg-white"},Gt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ht={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Jt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left"},Wt={key:0},Xt=e("br",null,null,-1),Yt={key:1},It={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},es=["onClick"],ts={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ss={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},os={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},as={class:"whitespace-nowrap py-2 text-xs text-center p-3"},rs={class:"flex flex-col space-y-1"},ls={key:2,class:"text-xs"},ns={key:3,class:"text-xs"},is={key:0},ds=e("td",{colspan:"6",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),cs=[ds],As={__name:"Edit",props:{categoryApiOptions:[Array,Object],deliveryProductMapping:Object,operatorOptions:Object,productMappingItems:Object,productMappingOptions:[Array,Object],productOptions:Object,type:String,unbindedVendOptions:[Array,Object]},setup(N){const n=N,Q=u([]),V=u([]),z=u([]),Z=u([]),t=u(M(T()));u(!1);const k=E().props.auth.operatorCountry,P=u([]),j=u([]),G=u([]),R=u([]),C=u(!1),q=u([]),A=u(""),H=E().props.auth.permissions,F=u(),J=u([]),K=u([]);re(()=>{n.type=="create"?A.value="Create New":A.value="Edit",Q.value=n.categoryApiOptions[0].categories.map(o=>({id:o.id,name:o.name,subCategories:o.subCategories})),V.value=[...n.operatorOptions.data.find(o=>o.id===n.deliveryProductMapping.data.operator_id).deliveryPlatformOperators.map(o=>({id:o.id,name:o.deliveryPlatform.name}))],Z.value=n.deliveryProductMapping?n.deliveryProductMapping.data.deliveryProductMappingItems:[],P.value=[...n.operatorOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],j.value=[...n.productMappingOptions[0].data.map(o=>({id:o.id,name:o.name}))],R.value=n.productOptions.data,t.value=n.deliveryProductMapping?M({...n.deliveryProductMapping.data,operator_id:P.value.find(o=>o.id===n.deliveryProductMapping.data.operator_id),operator_field:P.value.find(o=>o.id===n.deliveryProductMapping.data.operator_id).full_name,product_mapping_id:j.value.find(o=>o.id===n.deliveryProductMapping.data.product_mapping_id),product_mapping_field:j.value.find(o=>o.id===n.deliveryProductMapping.data.product_mapping_id).name,delivery_platform_operator_id:V.value.find(o=>o.id===n.deliveryProductMapping.data.delivery_platform_operator_id),delivery_platform_operator_field:V.value.find(o=>o.id===n.deliveryProductMapping.data.delivery_platform_operator_id).name}):M(T()),q.value=n.deliveryProductMapping?n.deliveryProductMapping.data.category_json.subCategories:[],K.value=[...n.unbindedVendOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],J.value=n.deliveryProductMapping?n.deliveryProductMapping.data.vends:[]});function T(){return{id:"",category_json:"",delivery_platform_operator_id:"",name:"",operator_id:"",product_mapping_id:"",reserved_percent:0,reserved_qty:0,sub_category_json:""}}function W(){G.value.map(function(o){return o.channel_code}).indexOf(t.value.channel_code)<0&&h.post("/delivery-product-mapping-items/delivery-product-mapping/"+t.value.id+"/store",{...t.value,product_id:t.value.product_id?t.value.product_id.id:null},{preserveState:!1,preserveScroll:!0,replace:!0})}function X(o){h.post("/delivery-product-mappings/"+t.value.id+"/bind-vend/"+o,{},{preserveState:!1,preserveScroll:!0,replace:!0})}function Y(o){F.value=o,C.value=!0}function I(){C.value=!1}function ee(){t.value.clearErrors(),t.value.transform(o=>({name:o.name,reserved_percent:o.reserved_percent,reserved_qty:o.reserved_qty})).post("/delivery-product-mappings/"+t.value.id+"/update",{preserveState:!0,replace:!0})}function te(o){let l=t.value.is_active?"Are you sure to pause all vending machines?":"Are you sure to resume all vending machines?";!confirm(l)||h.post("/delivery-product-mappings/"+o+"/toggle-pause-all-vends",{preserveState:!1,preserveScroll:!0,replace:!0})}function se(o){let l=o.is_active?"Are you sure to pause this SKU?":"Are you sure to resume this SKU?";!confirm(l)||h.post("/delivery-product-mapping-items/"+o.id+"/toggle-pause",{preserveState:!1,preserveScroll:!0,replace:!0})}function oe(o){let l=o.is_active?"Are you sure to pause this vending machine?":"Are you sure to resume this vending machine?";!confirm(l)||h.post("/delivery-product-mappings/vends/"+o.id+"/toggle-pause-vend",{preserveState:!1,preserveScroll:!0,replace:!0})}function ae(o){!confirm("Are you sure to delete this entry?")||h.delete("/delivery-product-mapping-items/"+o,{preserveState:!1,preserveScroll:!0,replace:!0})}return(o,l)=>(a(),r(O,null,[d(c(le),{title:"Delivery Product Mapping"}),d(ie,null,{header:m(()=>[e("h2",me,[x(p(A.value)+" Delivery Product Mapping ",1),N.type=="update"?(a(),r("span",_e,p(z.value.name),1)):i("",!0)])]),default:m(()=>[e("div",ve,[e("div",xe,[e("div",ge,[e("div",ye,[e("form",{onSubmit:f(ee,["prevent"]),id:"submit"},[e("div",he,[e("div",be,[d(w,{modelValue:t.value.name,"onUpdate:modelValue":l[0]||(l[0]=s=>t.value.name=s),error:t.value.errors.name},{default:m(()=>[x(" Name ")]),_:1},8,["modelValue","error"])]),e("div",we,[ke,e("div",Ce,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.operator_field,disabled:""},null,8,Se)])]),e("div",Oe,[Ve,e("div",Pe,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.delivery_platform_operator_field,disabled:""},null,8,je)])]),e("div",Ae,[Me,e("div",$e,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.category_json.name,disabled:""},null,8,De)])]),e("div",Be,[Ue,e("div",Ne,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.product_mapping_field,disabled:""},null,8,Re)])]),e("div",qe,[d(w,{modelValue:t.value.reserved_percent,"onUpdate:modelValue":l[1]||(l[1]=s=>t.value.reserved_percent=s),error:t.value.errors.reserved_percent},{default:m(()=>[x(" Reserved Percentage (%) ")]),_:1},8,["modelValue","error"])]),e("div",Fe,[d(w,{modelValue:t.value.reserved_qty,"onUpdate:modelValue":l[2]||(l[2]=s=>t.value.reserved_qty=s),error:t.value.errors.reserved_qty},{default:m(()=>[x(" Reserved Quantity ")]),_:1},8,["modelValue","error"])]),Ke,e("div",Te,[e("div",Ee,[d(c(ne),{href:"/delivery-product-mappings"},{default:m(()=>[d(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:m(()=>[d(c(ce),{class:"w-4 h-4"}),Le]),_:1})]),_:1}),c(H).includes("update vends")?(a(),g(y,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:m(()=>[d(c(pe),{class:"w-4 h-4"}),Qe]),_:1})):i("",!0)])]),ze,t.value.id?(a(),r("div",Ze,[d(w,{modelValue:t.value.channel_code,"onUpdate:modelValue":l[3]||(l[3]=s=>t.value.channel_code=s),error:t.value.errors.channel_code,placeholderStr:"Channel ID"},{default:m(()=>[x(" Channel ID ")]),_:1},8,["modelValue","error"])])):i("",!0),t.value.id?(a(),r("div",Ge,[He,d(B,{modelValue:t.value.product_id,"onUpdate:modelValue":l[4]||(l[4]=s=>t.value.product_id=s),options:R.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.product_id?(a(),r("div",Je,p(t.value.errors.product_id),1)):i("",!0)])):i("",!0),t.value.id?(a(),r("div",We,[d(w,{modelValue:t.value.amount,"onUpdate:modelValue":l[5]||(l[5]=s=>t.value.amount=s),error:t.value.errors.amount,placeholderStr:"Platform Price"},{default:m(()=>[x(" Price ")]),_:1},8,["modelValue","error"])])):i("",!0),t.value.id?(a(),r("div",Xe,[Ye,d(B,{modelValue:t.value.sub_category_json,"onUpdate:modelValue":l[6]||(l[6]=s=>t.value.sub_category_json=s),options:q.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):i("",!0),t.value.product_mapping_id?(a(),r("div",Ie,[d(y,{type:"button",onClick:l[7]||(l[7]=f(s=>W(),["prevent"])),class:v(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!t.value.channel_code||!t.value.product_id?"opacity-50 cursor-not-allowed":""]]),disabled:!t.value.channel_code||!t.value.product_id},{default:m(()=>[d(c(L),{class:"w-4 h-4"}),et]),_:1},8,["class","disabled"])])):i("",!0),t.value.product_mapping_id?(a(),r("div",tt,[e("div",st,[e("div",ot,[e("div",at,[e("table",rt,[lt,e("tbody",nt,[(a(!0),r(O,null,$(n.deliveryProductMapping.data.deliveryProductMappingItems,(s,b)=>(a(),r("tr",{key:s.id,class:v(b%2===0?void 0:"bg-gray-50")},[e("td",it,p(b+1),1),e("td",dt,p(s.channel_code),1),e("td",ct,[e("div",pt,[s.product&&s.product.thumbnail?(a(),r("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:s.product.thumbnail.full_url,alt:""},null,8,ut)):i("",!0)])]),e("td",mt,[s.product.code?(a(),r("span",_t,p(s.product.code),1)):i("",!0),s.product.name?(a(),r("span",vt,p(s.product.name),1)):i("",!0)]),e("td",xt,[s.is_active==1?(a(),r("span",gt," Active ")):i("",!0),s.is_active==0?(a(),r("span",yt," Paused ")):i("",!0)]),e("td",ft,p(s.amount.toLocaleString(void 0,{minimumFractionDigits:c(k).is_currency_exponent_hidden?0:c(k).currency_exponent,maximumFractionDigits:c(k).is_currency_exponent_hidden?0:c(k).currency_exponent})),1),e("td",ht,p(s.sub_category_json.name),1),e("td",bt,[d(y,{class:v(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:f(_=>se(s),["prevent"])},{default:m(()=>[s.is_active?(a(),g(c(U),{key:0,class:"w-3 h-3"})):(a(),g(c(D),{key:1,class:"w-3 h-3"})),s.is_active?(a(),r("span",wt," Pause SKU ")):(a(),r("span",kt," Resume SKU "))]),_:2},1032,["class","onClick"]),d(y,{class:"bg-red-400 hover:bg-red-500 text-white flex space-x-1",onClick:f(_=>ae(s.id),["prevent"])},{default:m(()=>[d(c(ue),{class:"w-3 h-3"}),Ct]),_:2},1032,["onClick"])])],2))),128)),!n.deliveryProductMapping.data.deliveryProductMappingItems||!n.deliveryProductMapping.data.deliveryProductMappingItems.length?(a(),r("tr",St,Vt)):i("",!0)])])])])])])):i("",!0),t.value.product_mapping_id?(a(),r("div",Pt,At)):i("",!0),e("div",Mt,[e("div",$t,[d(y,{class:v(["flex space-x-1",[t.value.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black-700":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:l[8]||(l[8]=f(s=>te(t.value.id),["prevent"]))},{default:m(()=>[t.value.is_active?(a(),g(c(U),{key:0,class:"w-4 h-4"})):(a(),g(c(D),{key:1,class:"w-4 h-4"})),t.value.is_active?(a(),r("span",Dt," Pause All Vending Machines ")):(a(),r("span",Bt," Resume All Vending Machines "))]),_:1},8,["class"])])]),t.value.product_mapping_id?(a(),r("div",Ut,[Nt,d(B,{modelValue:t.value.vend_id,"onUpdate:modelValue":l[9]||(l[9]=s=>t.value.vend_id=s),options:K.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.vend_id?(a(),r("div",Rt,p(t.value.errors.vend_id),1)):i("",!0)])):i("",!0),t.value.product_mapping_id?(a(),r("div",qt,[d(y,{type:"button",onClick:l[10]||(l[10]=f(s=>X(t.value.vend_id.id),["prevent"])),class:v(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[t.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!t.value.vend_id},{default:m(()=>[d(c(L),{class:"w-4 h-4"}),Ft]),_:1},8,["class","disabled"])])):i("",!0),t.value.product_mapping_id?(a(),r("div",Kt,[e("div",Tt,[e("div",Et,[e("div",Lt,[e("table",Qt,[zt,e("tbody",Zt,[(a(!0),r(O,null,$(n.deliveryProductMapping.data.deliveryProductMappingVends,(s,b)=>(a(),r("tr",{key:s.id,class:v(b%2===0?void 0:"bg-gray-50")},[e("td",Gt,p(b+1),1),e("td",Ht,p(s.vend.code),1),e("td",Jt,[s.vend.latestVendBinding&&s.vend.latestVendBinding.customer?(a(),r("span",Wt,[x(p(s.vend.latestVendBinding.customer.code)+" ",1),Xt,x(" "+p(s.vend.latestVendBinding.customer.name),1)])):(a(),r("span",Yt,p(s.vend.name),1))]),e("td",It,[s.deliveryProductMappingVendChannels?(a(),r("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:_=>Y(s)},[(a(!0),r(O,null,$(s.deliveryProductMappingVendChannels,(_,S)=>(a(),r("li",{class:v(["quick-look",[S>0&&String(_.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[S-1].vend_channel_code)[0]?"col-start-1":""]])},[e("span",{class:v([[S>0&&String(_.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[S-1].vend_channel_code)[0]?"border-t-4 pt-1":""],"flex space-x-1"])},[e("span",null," #"+p(_.vend_channel_code)+", ",1),e("span",null,p(_.delivery_product_mapping_item.product?_.delivery_product_mapping_item.product.code:""),1),e("span",{class:v(["inline-flex items-center rounded-md px-1.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10",[_.is_active==1?"bg-green-500":"bg-red-500"]])},null,2)],2)],2))),256))],8,es)):i("",!0)]),e("td",ts,[s.is_active==1?(a(),r("span",ss," Operating ")):i("",!0),s.is_active==0?(a(),r("span",os," Paused ")):i("",!0)]),e("td",as,[e("div",rs,[d(y,{class:v(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:f(_=>oe(s),["prevent"])},{default:m(()=>[s.is_active?(a(),g(c(U),{key:0,class:"w-3 h-3"})):(a(),g(c(D),{key:1,class:"w-3 h-3"})),s.is_active?(a(),r("span",ls," Pause VM ")):(a(),r("span",ns," Resume VM "))]),_:2},1032,["class","onClick"])])])],2))),128)),n.deliveryProductMapping.data.deliveryProductMappingVends.length?i("",!0):(a(),r("tr",is,cs))])])])])])])):i("",!0)])],40,fe)])])])]),C.value?(a(),g(de,{key:0,vend:F.value,showModal:C.value,onModalClose:I},null,8,["vend","showModal"])):i("",!0)]),_:1})],64))}};export{As as default};
