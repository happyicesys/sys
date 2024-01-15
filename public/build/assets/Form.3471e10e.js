import{g as f,T as b,Q as A,h as M,c as B,a as d,w as i,p as E,o as l,b as e,f as n,l as c,t as u,d as _,u as v,n as C,F,k as P,e as T}from"./app.c175229f.js";import{_ as x}from"./Button.9313e1cd.js";import{_ as y}from"./FormInput.85070516.js";import{_ as D}from"./Modal.a68e1f5c.js";import{_ as w}from"./MultiSelect.33151647.js";import{r as R}from"./PlusCircleIcon.dfa4de65.js";import{r as L}from"./ArrowUturnLeftIcon.523e113f.js";import{r as q}from"./CheckCircleIcon.b88784c9.js";import{r as z}from"./BackspaceIcon.37eaa813.js";import"./keyboard.a78ebfd9.js";import"./disposables.1e713d43.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.9310359a.js";const Q={class:"flex flex-col md:flex-row space-x-2"},G={key:0,class:"text-gray-600"},H={key:1},I={key:2,class:"text-gray-600"},J={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},K={class:"col-span-12 sm:col-span-6"},W={class:"col-span-12 sm:col-span-6"},X={class:"col-span-12 sm:col-span-6"},Y={class:"col-span-12 sm:col-span-6"},Z={key:0,class:"col-span-12 sm:col-span-6"},ee=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Role ",-1),se={class:"col-span-12 sm:col-span-6"},te=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),oe={key:0,class:"text-sm text-red-600"},ae={key:1,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},re=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Access Vending Machine(s) ")])],-1),le=[re],de={key:2,class:"sm:col-span-5"},ne=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine to Bind ",-1),ie={key:0,class:"text-sm text-red-600"},ce={key:3,class:"sm:col-span-1"},ue=e("span",null," Add ",-1),me={key:4,class:"sm:col-span-6 flex flex-col mt-3"},pe={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},_e={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},ve={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},fe={class:"min-w-full divide-y divide-gray-300"},xe=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Name "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),ye={class:"bg-white"},he={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ge={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},be={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},we={key:0},Ve=e("br",null,null,-1),ke={key:1},Oe={class:"whitespace-nowrap py-4 text-sm text-center"},Be={key:0},Ce=e("td",{colspan:"4",class:"whitespace-nowrap py-4 text-sm font-medium text-red-600 text-center"}," No Binding = Access to All ",-1),Se=[Ce],$e={class:"sm:col-span-6"},je={class:"flex space-x-1 mt-5 justify-end"},Ue=e("span",null," Back ",-1),Ne=e("span",null," Save ",-1),He={__name:"Form",props:{user:Object,countries:Object,operators:Object,permissions:[Array,Object],roles:Object,type:String,showModal:Boolean,unbindedVends:[Array,Object]},emits:["modalClose"],setup(m,{emit:S}){const r=m,V=S,s=f(b(g())),k=f([]),h=f([]),$=A().props.auth.operatorRole,p=f([]);M(()=>{s.value=r.user?b({...g(),...r.user}):b(g()),k.value=r.operators.data,h.value=r.roles.data,r.permissions.includes("admin-access operators")||(h.value=r.roles.data.filter(function(a){return a.name=="operator"||a.name=="operator_user"})),p.value=r.unbindedVends.data});function j(){r.user.vends.indexOf(s.value.vend_id)<0&&(r.user.vends.push(s.value.vend_id),r.user.vends.sort((a,o)=>a.code-o.code),p.value.splice(p.value.indexOf(s.value.vend_id),1),p.value.sort((a,o)=>a.code-o.code))}function U(a){r.user.vends.splice(r.user.vends.indexOf(a),1),p.value.push(a),p.value.sort((o,t)=>o.code-t.code)}function g(){return{name:"",email:"",username:"",password:"",operator_id:"",role_id:""}}function N(){s.value.clearErrors(),r.type==="create"&&s.value.transform(a=>({...a,operator_id:a.operator_id.id,role_id:a.role_id.id})).post("/users/create",{onSuccess:()=>{V("modalClose")},preserveState:!0,replace:!0}),r.type==="update"&&s.value.transform(a=>({...a,operator_id:a.operator_id.id,role_id:a.role_id.id,user:r.user})).post("/users/"+s.value.id+"/update",{onSuccess:()=>{V("modalClose")},preserveState:!0,replace:!0})}return(a,o)=>(l(),B(E,{to:"body"},[d(D,{open:m.showModal,onModalClose:o[9]||(o[9]=t=>a.$emit("modalClose"))},{header:i(()=>[e("div",Q,[r.user?(l(),n("span",G," Editing ")):c("",!0),r.user?(l(),n("span",H,u(r.user.name),1)):(l(),n("span",I," Create New User "))])]),default:i(()=>[e("form",{onSubmit:T(N,["prevent"]),id:"submit"},[e("div",J,[e("div",K,[d(y,{modelValue:s.value.name,"onUpdate:modelValue":o[0]||(o[0]=t=>s.value.name=t),error:s.value.errors.name,required:"true"},{default:i(()=>[_(" Name ")]),_:1},8,["modelValue","error"])]),e("div",W,[d(y,{modelValue:s.value.email,"onUpdate:modelValue":o[1]||(o[1]=t=>s.value.email=t),error:s.value.errors.email},{default:i(()=>[_(" Email ")]),_:1},8,["modelValue","error"])]),e("div",X,[d(y,{modelValue:s.value.username,"onUpdate:modelValue":o[2]||(o[2]=t=>s.value.username=t),error:s.value.errors.username},{default:i(()=>[_(" Username ")]),_:1},8,["modelValue","error"])]),e("div",Y,[d(y,{modelValue:s.value.password,"onUpdate:modelValue":o[3]||(o[3]=t=>s.value.password=t),error:s.value.errors.password,placeholderStr:[m.type=="update"?"Leave blank for same password":""],inputType:"password",autocomplete:"new-password"},{default:i(()=>[_(" Password "+u(m.type=="update"?"(Override)":""),1)]),_:1},8,["modelValue","error","placeholderStr"])]),v($)?c("",!0):(l(),n("div",Z,[ee,d(w,{modelValue:s.value.role_id,"onUpdate:modelValue":o[4]||(o[4]=t=>s.value.role_id=t),options:h.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),e("div",se,[te,d(w,{modelValue:s.value.operator_id,"onUpdate:modelValue":o[5]||(o[5]=t=>s.value.operator_id=t),options:k.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"top",class:"mt-1"},null,8,["modelValue","options"]),s.value.errors.operator_id?(l(),n("div",oe,u(s.value.errors.operator_id),1)):c("",!0)]),s.value.id?(l(),n("div",ae,le)):c("",!0),s.value.id?(l(),n("div",de,[ne,d(w,{modelValue:s.value.vend_id,"onUpdate:modelValue":o[6]||(o[6]=t=>s.value.vend_id=t),options:p.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),s.value.errors.vend_id?(l(),n("div",ie,u(s.value.errors.vend_id),1)):c("",!0)])):c("",!0),s.value.id?(l(),n("div",ce,[d(x,{type:"button",onClick:o[7]||(o[7]=t=>j()),class:C(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[s.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!s.value.vend_id&&!m.permissions.includes("update operators")},{default:i(()=>[d(v(R),{class:"w-4 h-4"}),ue]),_:1},8,["class","disabled"])])):c("",!0),s.value.id?(l(),n("div",me,[e("div",pe,[e("div",_e,[e("div",ve,[e("table",fe,[xe,e("tbody",ye,[(l(!0),n(F,null,P(m.user.vends,(t,O)=>(l(),n("tr",{key:t.id,class:C(O%2===0?void 0:"bg-gray-50")},[e("td",he,u(O+1),1),e("td",ge,u(t.code),1),e("td",be,[t.latestVendBinding&&t.latestVendBinding.customer?(l(),n("span",we,[_(u(t.latestVendBinding.customer.code)+" ",1),Ve,_(" "+u(t.latestVendBinding.customer.name),1)])):(l(),n("span",ke,u(t.name),1))]),e("td",Oe,[m.permissions.includes("update operators")?(l(),B(x,{key:0,class:"bg-red-400 hover:bg-red-500 text-white",onClick:Ae=>U(t)},{default:i(()=>[d(v(z),{class:"w-4 h-4"})]),_:2},1032,["onClick"])):c("",!0)])],2))),128)),m.user.vends.length?c("",!0):(l(),n("tr",Be,Se))])])])])])])):c("",!0)]),e("div",$e,[e("div",je,[d(x,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[8]||(o[8]=t=>a.$emit("modalClose")),form:"submit"},{default:i(()=>[d(v(L),{class:"w-4 h-4"}),Ue]),_:1}),d(x,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:i(()=>[d(v(q),{class:"w-4 h-4"}),Ne]),_:1})])])],32)]),_:1},8,["open"])]))}};export{He as default};