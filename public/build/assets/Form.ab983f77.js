import{o as a,f as l,b as e,g as x,T as v,h as O,c as k,a as r,w as i,p as A,l as d,t as p,d as g,n as C,u as m,F as B,k as P,e as j,O as F}from"./app.baff8b32.js";import{_}from"./Button.ccd018db.js";import{_ as V}from"./FormInput.986fd714.js";import{_ as D}from"./FormTextarea.5bc2d7a4.js";import{_ as T}from"./Modal.3fbc64a3.js";import{_ as U}from"./MultiSelect.c64b42d5.js";import{r as z}from"./PlusCircleIcon.318c09ca.js";import{r as E}from"./ArrowUturnLeftIcon.ef3b9238.js";import{r as L}from"./CheckCircleIcon.a59a4ba5.js";import{r as R}from"./BackspaceIcon.767c80fc.js";import"./open-closed.39849135.js";import"./disposables.b1ba51d4.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c72e8597.js";function H(f,h){return a(),l("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{d:"M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"}),e("path",{d:"M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"})])}const J={class:"flex flex-col md:flex-row space-x-2"},q={key:0,class:"text-gray-600"},G={key:1},K={key:2,class:"text-gray-600"},Q=["onSubmit"],W={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},X={class:"sm:col-span-6"},Y={class:"sm:col-span-6"},Z={key:0,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},I=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Vend Channels Product Mapping ")])],-1),ee=[I],te={key:1,class:"sm:col-span-2"},se={key:2,class:"sm:col-span-3"},oe=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Product ",-1),ae={key:0,class:"text-sm text-red-600"},le={key:3,class:"sm:col-span-1"},re=e("span",null," Add ",-1),ne={key:4,class:"sm:col-span-6 flex flex-col mt-3"},de={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},ce={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},ie={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},ue={class:"min-w-full divide-y divide-gray-300"},pe=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel Code "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Thumbnail "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Product "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),me={class:"bg-white"},_e={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},he={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ve={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},fe={class:"flex justify-center"},xe=["src"],ge={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left"},ye={key:0},be={class:"whitespace-nowrap py-4 text-sm text-center"},we={key:0},ke=e("td",{colspan:"5",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),Ce=[ke],Ve={class:"sm:col-span-6"},$e={class:"flex space-x-1 mt-5 justify-end"},Se=e("span",null," Back ",-1),Me=e("span",null," Replicate ",-1),Ne=e("span",null," Save ",-1),Je={__name:"Form",props:{products:Object,productMapping:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(f,{emit:h}){const c=f,t=x(v(b())),y=x([]),u=x([]);O(()=>{t.value=c.productMapping?v(c.productMapping):v(b()),y.value=c.products.data,u.value=c.productMapping?JSON.parse(JSON.stringify(c.productMapping.productMappingItems)):v()});function b(){return{id:"",name:"",remarks:"",channel_code:"",product_id:""}}function $(){t.value.clearErrors(),c.type==="create"&&t.value.post("/product-mappings/create",{onSuccess:()=>{h("modalClose")},preserveState:!0,replace:!0}),c.type==="update"&&t.value.transform(n=>({...n,productMappingItems:u.value})).post("/product-mappings/"+t.value.id+"/update",{onSuccess:()=>{h("modalClose")},preserveState:!0,replace:!0})}function S(){u.value.map(function(n){return n.channel_code}).indexOf(t.value.channel_code)<0&&(u.value.push({product:t.value.product_id,channel_code:t.value.channel_code}),u.value.sort((n,o)=>n.channel_code-o.channel_code))}function M(n){u.value.splice(u.value.indexOf(n),1)}function N(){F.post("/product-mappings/replicate",{id:t.value.id},{preserveState:!0,replace:!0,onSuccess:n=>{h("modalClose")}})}return(n,o)=>(a(),k(A,{to:"body"},[r(T,{open:f.showModal,onModalClose:o[7]||(o[7]=s=>n.$emit("modalClose"))},{header:i(()=>[e("div",J,[c.productMapping?(a(),l("span",q," Editing ")):d("",!0),c.productMapping?(a(),l("span",G,p(c.productMapping.name),1)):(a(),l("span",K," Create New Product Mapping "))])]),default:i(()=>[e("form",{onSubmit:j($,["prevent"]),id:"submit"},[e("div",W,[e("div",X,[r(V,{modelValue:t.value.name,"onUpdate:modelValue":o[0]||(o[0]=s=>t.value.name=s),error:t.value.errors.name,required:"true"},{default:i(()=>[g(" Name ")]),_:1},8,["modelValue","error"])]),e("div",Y,[r(D,{modelValue:t.value.remarks,"onUpdate:modelValue":o[1]||(o[1]=s=>t.value.remarks=s),error:t.value.errors.remarks},{default:i(()=>[g(" Remarks ")]),_:1},8,["modelValue","error"])]),t.value.id?(a(),l("div",Z,ee)):d("",!0),t.value.id?(a(),l("div",te,[r(V,{modelValue:t.value.channel_code,"onUpdate:modelValue":o[2]||(o[2]=s=>t.value.channel_code=s),error:t.value.errors.channel_code,placeholderStr:"Channel ID"},{default:i(()=>[g(" Channel ID ")]),_:1},8,["modelValue","error"])])):d("",!0),t.value.id?(a(),l("div",se,[oe,r(U,{modelValue:t.value.product_id,"onUpdate:modelValue":o[3]||(o[3]=s=>t.value.product_id=s),options:y.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.product_id?(a(),l("div",ae,p(t.value.errors.product_id),1)):d("",!0)])):d("",!0),t.value.id?(a(),l("div",le,[r(_,{type:"button",onClick:o[4]||(o[4]=s=>S()),class:C(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!t.value.channel_code||!t.value.product_id?"opacity-50 cursor-not-allowed":""]]),disabled:!t.value.channel_code||!t.value.product_id},{default:i(()=>[r(m(z),{class:"w-4 h-4"}),re]),_:1},8,["class","disabled"])])):d("",!0),t.value.id?(a(),l("div",ne,[e("div",de,[e("div",ce,[e("div",ie,[e("table",ue,[pe,e("tbody",me,[(a(!0),l(B,null,P(u.value,(s,w)=>(a(),l("tr",{key:s.id,class:C(w%2===0?void 0:"bg-gray-50")},[e("td",_e,p(w+1),1),e("td",he,p(s.channel_code),1),e("td",ve,[e("div",fe,[s.product&&s.product.thumbnail?(a(),l("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:s.product.thumbnail.full_url,alt:""},null,8,xe)):d("",!0)])]),e("td",ge,[s.product.code?(a(),l("span",ye,p(s.product.code)+" - ",1)):d("",!0),e("span",null,p(s.product.name),1)]),e("td",be,[r(_,{class:"bg-red-400 hover:bg-red-500 text-white",onClick:Oe=>M(s)},{default:i(()=>[r(m(R),{class:"w-4 h-4"})]),_:2},1032,["onClick"])])],2))),128)),u.value.length?d("",!0):(a(),l("tr",we,Ce))])])])])])])):d("",!0)]),e("div",Ve,[e("div",$e,[r(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[5]||(o[5]=s=>n.$emit("modalClose")),form:"submit"},{default:i(()=>[r(m(E),{class:"w-4 h-4"}),Se]),_:1}),t.value.id?(a(),k(_,{key:0,type:"button",class:"bg-blue-500 hover:bg-blue-600 text-white flex space-x-1",onClick:o[6]||(o[6]=s=>N())},{default:i(()=>[r(m(H),{class:"w-4 h-4"}),Me]),_:1})):d("",!0),r(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:i(()=>[r(m(L),{class:"w-4 h-4"}),Ne]),_:1})])])],40,Q)]),_:1},8,["open"])]))}};export{Je as default};
